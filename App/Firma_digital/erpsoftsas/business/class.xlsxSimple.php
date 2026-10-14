<?php
namespace erpsoftsas;

/**
 * Un .xlsx de UNA hoja, escrito a mano (informe de pagos, 2026-10-13).
 *
 * Sin librerías ni la extensión zip de PHP: no se sabe si el PHP de Plesk de
 * cada municipio la trae, y un informe que falla solo en producción es lo que
 * este repo ya aprendió a evitar (la trampa de TCPDF y los temporales). El
 * ZIP se arma aquí con gzdeflate y crc32, que vienen siempre con PHP.
 *
 * Estilos fijos (índices de cellXfs en estilos()):
 *   0 normal          1 título            2 subtítulo (gris)
 *   3 encabezado      4 texto con borde   5 fecha con borde
 *   6 pesos con borde 7 total (rótulo)    8 total (pesos)
 *   9 número centrado con borde
 */
class XlsxSimple
{
    const TITULO = 1, SUBTITULO = 2, ENCABEZADO = 3, TEXTO = 4, FECHA = 5, PESOS = 6,
          TOTAL = 7, TOTAL_PESOS = 8, CENTRADO = 9;

    private $filas  = [];   // n => [col => '<c .../>'] ya escrita: un arreglo por celda gastaba ~58 MB con 10.000 filas
    private $anchos = [];
    private $unir   = [];
    private $congelarFila = 0;
    private $filtro = null;
    private $color;
    private $nombreHoja;

    public function __construct($nombreHoja = 'Informe', $colorHex = '1FA49D')
    {
        $this->nombreHoja = mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', (string) $nombreHoja), 0, 31);
        $c = strtoupper(ltrim((string) $colorHex, '#'));
        $this->color = preg_match('/^[0-9A-F]{6}$/', $c) ? $c : '1FA49D';
    }

    /** Ancho de cada columna, en caracteres (A = 1). */
    public function anchos(array $anchos) { $this->anchos = $anchos; }

    /** Texto en una celda (fila y columna desde 1). */
    public function texto($fila, $col, $valor, $estilo = 0)
    {
        $valor = (string) $valor;
        $this->filas[$fila][$col] = $valor === ''
            ? '<c r="' . self::columna($col) . $fila . '"' . $this->s($estilo) . '/>'
            : '<c r="' . self::columna($col) . $fila . '"' . $this->s($estilo) . ' t="inlineStr"><is><t xml:space="preserve">' . $this->x($valor) . '</t></is></c>';
    }

    /** Número. */
    public function numero($fila, $col, $valor, $estilo = 0)
    {
        $this->filas[$fila][$col] = '<c r="' . self::columna($col) . $fila . '"' . $this->s($estilo) . '><v>' . self::num($valor) . '</v></c>';
    }

    /** Fecha 'AAAA-MM-DD' como fecha de Excel (número de serie); vacía, celda en blanco con el estilo. */
    public function fecha($fila, $col, $iso, $estilo = self::FECHA)
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $iso, $x)) { $this->texto($fila, $col, '', $estilo); return; }
        $this->numero($fila, $col, (gmmktime(0, 0, 0, (int) $x[2], (int) $x[3], (int) $x[1]) / 86400) + 25569, $estilo);
    }

    /**
     * Fórmula (sin el "="), con el resultado ya calculado: sin él, las vistas
     * previas (correo, celular) y un LibreOffice que no recalcula la muestran
     * vacía. Un resultado de texto va como t="str".
     */
    public function formula($fila, $col, $formula, $estilo = 0, $resultado = null)
    {
        $v = '';
        $t = '';
        if (is_string($resultado))   { $t = ' t="str"'; $v = '<v>' . $this->x($resultado) . '</v>'; }
        elseif ($resultado !== null) { $v = '<v>' . self::num($resultado) . '</v>'; }
        $this->filas[$fila][$col] = '<c r="' . self::columna($col) . $fila . '"' . $this->s($estilo) . $t . '><f>' . $this->x($formula) . '</f>' . $v . '</c>';
    }

    private function s($estilo) { return $estilo ? ' s="' . (int) $estilo . '"' : ''; }

    private static function num($valor)
    {
        return rtrim(rtrim(sprintf('%.6F', (float) $valor), '0'), '.');
    }

    public function unir($desde, $hasta) { $this->unir[] = $desde . ':' . $hasta; }
    public function congelarDebajoDe($fila) { $this->congelarFila = (int) $fila; }
    public function autofiltro($rango) { $this->filtro = $rango; }

    public static function columna($n)
    {
        $s = '';
        for ($n = (int) $n; $n > 0; $n = intdiv($n - 1, 26)) { $s = chr(65 + (($n - 1) % 26)) . $s; }
        return $s;
    }

    /** El archivo, como bytes. */
    public function contenido()
    {
        $archivos = [
            '[Content_Types].xml'        => $this->tiposDeContenido(),
            '_rels/.rels'                => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml'            => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="' . $this->x($this->nombreHoja) . '" sheetId="1" r:id="rId1"/></sheets>'
                . ($this->filtro ? '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . $this->x($this->nombreHoja) . '\'!' . $this->absoluto($this->filtro) . '</definedName></definedNames>' : '')
                . '<calcPr calcId="191029" fullCalcOnLoad="1"/>'
                . '</workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>',
            'xl/styles.xml'              => $this->estilos(),
            'xl/worksheets/sheet1.xml'   => $this->hoja(),
        ];
        return self::zip($archivos);
    }

    /* --------------------------------------------------------------------- */

    private function x($t)
    {
        // Sin caracteres de control (XML 1.0 no los admite).
        $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $t);
        return htmlspecialchars($t, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function absoluto($rango)
    {
        return preg_replace('/([A-Z]+)(\d+)/', '\$$1\$$2', $rango);
    }

    private function tiposDeContenido()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function estilos()
    {
        $c = $this->color;
        $borde = '<border><left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right>'
               . '<top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom><diagonal/></border>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/>'
            . '<numFmt numFmtId="165" formatCode="&quot;$&quot; #,##0"/></numFmts>'
            . '<fonts count="5">'
            . '<font><sz val="10"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="15"/><color rgb="FF' . $c . '"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><sz val="10"/><color rgb="FF6B7280"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="10"/><name val="Calibri"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $c . '"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFEEF2F6"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>' . $borde . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="10">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="4" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="165" fontId="4" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function hoja()
    {
        ksort($this->filas);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
             // Al imprimir, todas las columnas en el ancho de la hoja.
             . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>';
        if ($this->congelarFila > 0) {
            $abajo = $this->congelarFila + 1;
            $xml .= '<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="' . $this->congelarFila
                  . '" topLeftCell="A' . $abajo . '" activePane="bottomLeft" state="frozen"/>'
                  . '<selection pane="bottomLeft" activeCell="A' . $abajo . '" sqref="A' . $abajo . '"/></sheetView></sheetViews>';
        } else {
            $xml .= '<sheetViews><sheetView workbookViewId="0" showGridLines="0"/></sheetViews>';
        }
        $xml .= '<sheetFormatPr defaultRowHeight="15"/>';
        if ($this->anchos) {
            $xml .= '<cols>';
            foreach ($this->anchos as $i => $w) {
                $n = $i + 1;
                $xml .= '<col min="' . $n . '" max="' . $n . '" width="' . (float) $w . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }
        $xml .= '<sheetData>';
        foreach ($this->filas as $n => $celdas) {
            ksort($celdas);
            $alto = $n === $this->congelarFila ? ' ht="30" customHeight="1"' : ($n === 1 ? ' ht="24" customHeight="1"' : '');
            $xml .= '<row r="' . $n . '"' . $alto . '>' . implode('', $celdas) . '</row>';
            unset($this->filas[$n]);
        }
        $xml .= '</sheetData>';
        if ($this->filtro) { $xml .= '<autoFilter ref="' . $this->filtro . '"/>'; }
        if ($this->unir) {
            $xml .= '<mergeCells count="' . count($this->unir) . '">';
            foreach ($this->unir as $u) { $xml .= '<mergeCell ref="' . $u . '"/>'; }
            $xml .= '</mergeCells>';
        }
        $xml .= '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
              . '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
              . '</worksheet>';
        return $xml;
    }

    /** ZIP con DEFLATE, sin la extensión zip. */
    private static function zip(array $archivos)
    {
        $datos = '';
        $central = '';
        $hora  = (12 << 11);
        $fecha = ((2026 - 1980) << 9) | (1 << 5) | 1;
        foreach ($archivos as $nombre => $contenido) {
            $comprimido = gzdeflate($contenido, 6);
            $crc   = crc32($contenido) & 0xFFFFFFFF;
            $desde = strlen($datos);
            $cab = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 8, $hora, $fecha, $crc,
                        strlen($comprimido), strlen($contenido), strlen($nombre), 0);
            $datos .= $cab . $nombre . $comprimido;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 8, $hora, $fecha, $crc,
                             strlen($comprimido), strlen($contenido), strlen($nombre), 0, 0, 0, 0, 0, $desde) . $nombre;
        }
        $fin = pack('VvvvvVVv', 0x06054b50, 0, 0, count($archivos), count($archivos), strlen($central), strlen($datos), 0);
        return $datos . $central . $fin;
    }
}
