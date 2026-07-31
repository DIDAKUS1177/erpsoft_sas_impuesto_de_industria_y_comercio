<?php
// 1) Evitar que cualquier salida previa (warnings, espacios) interrumpa la generación del PDF
ob_start();

// 2) Suprimir únicamente los Warnings de TCPDF sobre “continue” y “chr()”
set_error_handler(function($errno, $errstr) {
    if (strpos($errstr, '"continue" targeting switch') !== false) return true;
    if (strpos($errstr, 'chr() expects parameter') !== false)     return true;
    return false;
}, E_WARNING);

// 3) Includes y carga de TCPDF (ajusta la ruta si hace falta)
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_FacturaDocumento.php';
include_once SERVER . '/business/DAO/DAO_FacturaDetalleDocumento.php';
include_once SERVER . '/business/DAO/DAO_Tesoreria.php';
include_once SERVER . '/business/class.sessions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/predial/extensiones/tcpdf/tcpdf.php';

// 4) Restaurar el handler por defecto
restore_error_handler();

class imprimirFactura {
    public $cedula;
    public $nombre;
    public $idExogena;
    public $formato;

    public function traerImpresionFactura() {
        // Leer parámetros
        $cedula    = $this->cedula;
        $nombre    = $this->nombre;
        $idExogena = $this->idExogena;
        $formato   = $this->formato;

        // Instanciar TCPDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('GestorDocumental');
        $pdf->SetAuthor('Secretaría de Hacienda');
        $pdf->SetTitle('Certificado Información Exógena');
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setFontSubsetting(false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->AddPage();

        // --- 1) BANNER SUPERIOR ---------------------------------------------------
        // ruta absoluta o relativa desde este script a tu imagen de banner
        $bannerPath = 'images/logo1.png';
        // ancho al 100% del área imprimible
        $pdf->Image($bannerPath, 15, 15, $pdf->getPageWidth() - 30, 30, '', '', '', false, 300);
        // mover el cursor hacia abajo para no sobreponer el HTML
        $pdf->Ln(40);
        // -------------------------------------------------------------------------

        // Contenido HTML
        date_default_timezone_set('America/Bogota');
        setlocale(LC_TIME, 'es_CO.UTF-8');

        $fecha = date('j \d\e F \d\e Y');
        $hora  = date('H:i');

        // Selección de código de formato
        switch ($formato) {
            case 1: $tipoFormato = 'PFE1'; break;
            case 2: $tipoFormato = 'PFE2'; break;
            case 3: $tipoFormato = 'PFE3'; break;
            case 4: $tipoFormato = 'PFE4'; break;
            case 5: $tipoFormato = 'PFE5'; break;
            default: $tipoFormato = '';
        }

        $html  = '
            <div style="text-align:center; line-height:1.2;">
                <strong>REPÚBLICA DE COLOMBIA</strong><br/>
                <strong>ALCALDÍA DE PAIPA</strong><br/>
                <strong>SECRETARÍA DE HACIENDA</strong><br/><br/>
                <strong>LA DIRECCIÓN DE IMPUESTOS, RENTAS Y JURISDICCIÓN COACTIVA</strong><br/><br/>
                <strong style="font-size:14pt;">CERTIFICA:</strong>
            </div><br/>

            <p>Que el contribuyente <strong>' . htmlspecialchars($nombre) . '</strong> identificado con <strong>NIT ' . htmlspecialchars($cedula) . '</strong> realizó la presentación de información exógena con los siguientes parámetros:</p>
            <table cellpadding="4" cellspacing="0" border="0">
                <tr><td width="35%"><strong>Acuse de recibido:</strong></td><td> 202500' . htmlspecialchars($idExogena) . '</td></tr>
                <tr><td><strong>Fecha de presentación:</strong></td><td>' . $fecha . '</td></tr>
                <tr><td><strong>Hora de presentación:</strong></td><td>' . $hora . '</td></tr>
                <tr><td><strong>Formato(s) presentado:</strong></td><td>' . $tipoFormato . '</td></tr>
            </table><br/><br/><br/>

            <p style="text-align:right;">Expedido el <em>' . $fecha . '</em></p>

            <!-- 2) LOGO SOBRE LA FIRMA ----------------------------------------------- -->
            <div style="text-align:center; margin-bottom:8px;">
                <img src="images/firma1.png'.'"
                     style="width:350px; height:auto;" />
            </div>
            <div style="text-align:center;">
                ___________________________________________<br/>
                <strong>JUAN GABRIEL SUÁREZ AVENDAÑO</strong><br/>
                Director de impuestos, rentas y jurisdicción coactiva
            </div>
        ';




        $pdf->writeHTML($html, true, false, true, false, '');

        // 5) Limpiar el buffer de salida antes de enviar headers
        ob_end_clean();

        // 6) Enviar el PDF al navegador
        $pdf->Output('Certificado_Exogena_' . preg_replace('/\s+/', '_', $cedula) . '.pdf', 'I');
        exit;
    }
}

// Instanciar y ejecutar
$factura = new imprimirFactura();
$factura->cedula    = $_GET['cedula'];
$factura->nombre    = $_GET['nombre'];
$factura->idExogena = $_GET['idExogena'];
$factura->formato   = $_GET['formato'];
$factura->traerImpresionFactura();
