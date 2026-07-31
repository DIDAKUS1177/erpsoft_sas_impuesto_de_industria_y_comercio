<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_PazYSalvo.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/controller/class.logs.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/erpsoftsas/extensiones/tcpdf/tcpdf.php';


class ControladorPazYSalvo extends \erpsoftsas\Cabecera {

    private $_funcion;
    private $_ok;
    private $_mensaje;
/*
    public static function run() {
        $_obj = new self();
        $_obj->_funcion = $_POST['funcion'] ?? $_GET['funcion'] ?? null;

        try {
            $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            $con->begin();

            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_buscarPredios();
                    break;
                case 2:
                    $respuesta = $_obj->_generarPazYSalvo();
                    break;
                default:
                    $_obj->_ok = 0;
                    $_obj->_mensaje = "Función no válida";
                    $respuesta = [];
            }

            $con->commit();
            header('Content-type: application/json');
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));

        } catch (\erpsoftsas\PazYSalvoException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "Error: " . $e->getMessage(), "datos" => "");
            header('Content-type: application/json');
            echo json_encode($arrRespu);
        }
    }
*/
    public static function run() {
        $_obj = new self();
        $_obj->_funcion = $_POST['funcion'] ?? $_GET['funcion'] ?? null;

        try {
            //$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            //$con->begin();

            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_buscarPredios();
                    //$con->commit();
                    header('Content-type: application/json');
                    echo json_encode([
                        "ok" => $_obj->_ok,
                        "mensaje" => $_obj->_mensaje,
                        "datos" => $respuesta
                    ]);
                    break;

                case 2:
                    // ⚠️ Caso especial: genera el PDF directamente y detiene ejecución
                    //$con->commit();
                    $_obj->_generarPazYSalvo();
                    exit;

                default:
                    //$con->rollback();
                    header('Content-type: application/json');
                    echo json_encode([
                        "ok" => 0,
                        "mensaje" => "Función no válida",
                        "datos" => []
                    ]);
                    break;
            }

        } catch (\erpsoftsas\PazYSalvoException $e) {
            //$con->rollback();
            header('Content-type: application/json');
            echo json_encode([
                "ok" => $e->getCode(),
                "mensaje" => "Error: " . $e->getMessage(),
                "datos" => ""
            ]);
        }
    }


    /**
     * _buscarPredios: Busca predios por nombre, dirección o código
     */
    protected function _buscarPredios() {
        $_objPaz = new \erpsoftsas\DAO_PazYSalvo();

        $dato = $_POST['dato'] ?? '';
        $resultado = $_objPaz->buscarPredios($dato);

        if (is_array($resultado) && count($resultado) > 0) {
            $this->_ok = 1;
            $this->_mensaje = "Predios encontrados";
        } else {
            $this->_ok = 0;
            $this->_mensaje = "No se encontraron predios";
        }

        return $resultado;
    }

   protected function _generarPazYSalvo() {
    try {
        $_objPaz = new \erpsoftsas\DAO_PazYSalvo();
        $codigoPredio = $_GET['codigo_predio'] ?? '';

        $cabecera = $_objPaz->obtenerCabecera($codigoPredio);
        $propietarios = $_objPaz->obtenerPropietarios($codigoPredio);

        if (!is_array($cabecera) || count($cabecera) == 0) {
            $this->_ok = 0;
            $this->_mensaje = "No se encontró información del predio";
            return [];
        }

        $data = $cabecera[0];
        $data['propietarios'] = $propietarios;

              // Limpia cualquier salida previa
        if (ob_get_length()) ob_end_clean();
        ob_start();

        header_remove();

        // ===== CONFIGURACIÓN TCPDF =====
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('ERPSoftSAS');
        $pdf->SetAuthor('ERPSoftSAS');
        $pdf->SetTitle('Certificado de Paz y Salvo - Impuesto Predial');
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 25);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 11);

        // ===== LOGO =====
  
        // ===== CONFIGURACIÓN DE RUTAS =====

        
        $basePath = $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/extensiones/tcpdf/pdf/img/';
        $logoPath  = $basePath . 'logopazysalvo.png';
        $firmaPath = $basePath . 'firma.png';


        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 20, 15, 30, '', 'PNG');
        }else {
            error_log("Logo no encontrado en: " . $logoPath);
        }

        // ===== ENCABEZADO =====
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetXY(70, 15);
        $pdf->MultiCell(120, 6, 
            "REPÚBLICA DE COLOMBIA\nALCALDÍA DE PAIPA\nTESORERÍA MUNICIPAL\nCERTIFICADO DE PAZ Y SALVO - IMPUESTO PREDIAL", 
            0, 'C', false);
        $pdf->Ln(5);

        // ===== TITULO CENTRAL =====
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Ln(15);
        $pdf->Cell(0, 7, 'LA TESORERÍA MUNICIPAL DE PAIPA', 0, 1, 'C');
        $pdf->Cell(0, 7, 'CERTIFICA', 0, 1, 'C');
        $pdf->Ln(5);

        // ===== CERTIFICADO NO =====
       $pdf->SetFont('helvetica', 'B', 11);
        // crear una fila con dos columnas: etiqueta izquierda y valor centrado
        $htmlCert = '
        <table cellpadding="3">
            <tr>
                <td width="25%" align="left"><b>Certificado No:</b></td>
                <td width="75%" align="left"><b>' . $data['Certificado'] . '</b></td>
            </tr>
        </table>';
        $pdf->writeHTML($htmlCert, false, false, false, false, '');
        $pdf->Ln(2);


        // ===== CUERPO PRINCIPAL =====
        $pdf->SetFont('helvetica', '', 8);

        $texto = '
        <p style="text-align:justify;">
            Que en los archivos de la tesorería municipal aparece inscrito el predio con código catastral número: 
            <b>' . $data['CodigoCatastral'] . '</b> el cual figura a nombre de 
            <b>' . strtoupper($data['Propietario']) . '</b>, documento de identidad número 
            <b>' . $data['Identificacion'] . '</b> con las siguientes especificaciones.
        </p>';

        $pdf->writeHTML($texto, true, false, false, false, '');
        $pdf->Ln(4);

        // === Último pago con factura No y Fecha ===
        $htmlPago = '
        <style>
            td { font-size:8px; }
        </style>
        <table cellpadding="2" cellspacing="0">
            <tr>
                <td width="40%"><b>Último pago con factura No:</b></td>
                <td width="60%">' . $data['FacturaUltimoPago'] . '</td>
            </tr>
            <tr>
                <td width="40%"><b>Fecha:</b></td>
                <td width="60%">' . $data['FechaUltimoPago'] . '</td>
            </tr>
        </table>';
        $pdf->writeHTML($htmlPago, true, false, false, false, '');
        $pdf->Ln(2);



        // ===== TABLA PRINCIPAL =====
       $html = '
        <style>
            th { background-color:#f2f2f2; font-weight:bold; font-size:7px; text-align:center; }
            td { font-size:7px; text-align:center; }
        </style>
        <table border="1" cellpadding="4">
            <tr>
                <th rowspan="2" width="28%">DIRECCIÓN DEL PREDIO</th>
                <th rowspan="2" width="17%">UBICACIÓN</th>
                <th colspan="3" width="30%">ÁREA</th>
                <th rowspan="2" width="15%">VALOR AVALUO</th>
                <th rowspan="2" width="10%">AÑO AVALUO</th>
            </tr>
            <tr>
                <th width="10%">HA</th>
                <th width="10%">M2</th>
                <th width="10%">CONS</th>
            </tr>
            <tr>
                <td style="text-align:left;">' . strtoupper($data['DireccionPredio']) . '</td>
                <td>' . $data['Ubicaion'] . '</td>
                <td>' . $data['Ha'] . '</td>
                <td>' . $data['M2'] . '</td>
                <td>' . $data['Const'] . '</td>
                <td>$' . $data['ValorAvaluo'] . '</td>
                <td>' . $data['AnioAvaluo'] . '</td>
            </tr>
        </table>';
        $pdf->writeHTML($html, true, false, false, false, '');
        $pdf->Ln(5);

        // ===== TABLA PROPIETARIOS =====
        if (count($propietarios) > 0) {
            $pdf->SetFont('helvetica', '', 8); // 
            $htmlProp = '
            <p>El cual está registrado con los siguientes propietarios:</p>
            <table border="1" cellpadding="4">
                <tr style="background-color:#f2f2f2;">
                    <th width="40">No.</th>
                    <th width="100">Cédula / NIT</th>
                    <th width="270">Nombre</th>
                </tr>';
            $i = 1;
            foreach ($propietarios as $p) {
                $htmlProp .= '<tr>
                    <td>' . str_pad($i++, 3, '0', STR_PAD_LEFT) . '</td>
                    <td>' . $p['Identificacion'] . '</td>
                    <td>' . strtoupper($p['Propietario']) . '</td>
                </tr>';
            }
            $htmlProp .= '</table>';
            $pdf->writeHTML($htmlProp, true, false, false, false, '');
            //$pdf->SetFont('helvetica', '', 11); // 
        }

        // ===== TEXTO FINAL =====
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(0, 6, 'El cual se encuentra a PAZ Y SALVO por concepto de impuesto predial.', 0, 'L');
        $pdf->Ln(1);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Write(6, 'Expedido el ');
        $pdf->SetFont('helvetica', 'B', 8);
        setlocale(LC_TIME, 'es_CO', 'spanish');
        $dias = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
        $pdf->Write(6, $dias[date('w')] . ' ' . date('d') . ' de ' . strftime('%B') . ' de ' . date('Y'));
        $pdf->Ln(1);
        $pdf->SetFont('helvetica', '', 8);
        //$pdf->Write(6, 'Se expide con destino a: ');
        //$pdf->SetFont('helvetica', 'B', 11);
        //$pdf->Write(6, $data['ValorCobro']);
        $pdf->Ln(8);

        $pdf->SetFont('helvetica', '', 8);
  $pdf->writeHTML(
    "Actualmente el municipio de paipa no tiene adoptada la contribución de valorización para predios ubicados dentro de su jurisdicción.<br>" .
    "La presente certificación se expide en relación con el predio identificado con cédula catastral No. <b>" . $data['CodigoCatastral'] . "</b>.",
    true, false, true, false, 'J'
);

        $pdf->Ln(8);

      // ===== BLOQUE FINAL: texto a la izquierda / firma a la derecha =====
      // Elaboró: PSE - <b>' . strtoupper(explode(" ", trim($data["Propietario"]))[0]) . '.</b>
        $htmlFinal = '
        <style>
            td { font-size:8px; vertical-align:top; }
        </style>
        <table width="100%">
            <tr>
                <td width="60%">
                    <b>Válido hasta:</b> ' . $data['AnioAvaluo'] . '-12-31<br>
                    <b>Valor:</b> ' . $data['ValorCobro'] . ' Pesos m/cte.<br>
                    Página 1 de 1<br>
                </td>
                <td width="40%" align="right">';
                    if (file_exists($firmaPath)) {
                        $htmlFinal .= '<img src="' . $firmaPath . '" width="160"><br><small></small>';
                    } else {
                        $htmlFinal .= '<small><b>Firma no disponible</b></small>';
                    }
        $htmlFinal .= '
                </td>
            </tr>
        </table>';
        $pdf->writeHTML($htmlFinal, true, false, false, false, '');



        // ===== PIE DE PÁGINA =====
        //$pdf->SetY(-50);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 5, 'TESORERÍA MUNICIPAL', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'tesoreria@paipa-boyaca.gov.co', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Carrera 22 # 25 - 14', 0, 1, 'C');

        ob_end_clean();
/*      
        $pdf->Output('PazYSalvo_' . $data['CodigoCatastral'] . '.pdf', 'I');
        exit;
*/

        $nombreArchivo = 'PazYSalvo_' . $data['CodigoCatastral'] . '.pdf';
        $pdf->Output($nombreArchivo, 'D');
        // Cierra la página automáticamente después de la descarga
        //echo "<script>window.close();</script>";
        exit;


    } catch (\Exception $e) {
        $this->_ok = 0;
        $this->_mensaje = "Error al generar el Paz y Salvo: " . $e->getMessage();
        return [];
    }
}



}

class PazYSalvoException extends \Exception {}

\erpsoftsas\ControladorPazYSalvo::run();
