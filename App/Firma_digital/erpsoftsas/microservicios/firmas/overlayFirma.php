<?php
/**
 * Microservicio Firmas — helper de superposición de firma en PDF TCPDF.
 *
 * Uso desde cualquier archivo PHP que genere un PDF con TCPDF:
 *   require_once __DIR__ . '/../microservicios/firmas/overlayFirma.php';
 *   firmas_overlayPdf($pdf, $firmaBase64, $sigY);
 *
 * @param TCPDF      $pdf         Instancia activa de TCPDF.
 * @param string|null $firmaBase64 Cadena base64 (con prefijo data:image/…) o null.
 * @param float       $sigY        Coordenada Y del área de firma en el PDF (mm).
 */
function firmas_overlayPdf($pdf, ?string $firmaBase64, float $sigY): void {
    if (empty($firmaBase64)) {
        return;
    }

    $base64Pure = preg_replace('/^data:image\/\w+;base64,/', '', $firmaBase64);
    $imgData    = base64_decode($base64Pure);
    $tmpFile    = tempnam(sys_get_temp_dir(), 'firma_') . '.png';
    file_put_contents($tmpFile, $imgData);

    // X=106 (dentro del borde de la columna funcionario), Y=$sigY+0.5, W=96mm, H=12.5mm
    $pdf->Image($tmpFile, 106, $sigY + 0.5, 96, 12.5, '', '', '', false, 150);

    unlink($tmpFile);
}
