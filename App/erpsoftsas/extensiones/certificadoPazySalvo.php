<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';

// Usa TCPDF para generar el PDF
require_once('./tcpdf/tcpdf.php');

class imprimirFactura {

    public $codigo;

    public function generarPDF() {
        $codigo = $this->codigo;

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();

        // Consulta los datos necesarios para el PDF
        $query = "
            SELECT 
                pe_Id AS numeroRadicado,
                created_at AS fechaRadicado,
                pe_NombreCompleto AS remite,
                (select dep.dep_Nombre from  dependencia as dep where dep.dep_Id = peticiones.pe_IdDependencia) as dependencia,
                (select catt.cat_Nombre from categorias_documental as catt where catt.cat_Id = peticiones.pe_IdCategoria) as serie,
                (select subc.subc_Nombre from sub_categorias_documental as subc where subc.subc_Id = peticiones.pe_IdSubCategoria) as subSerie

            FROM peticiones
            WHERE pe_Id = 1
        ";

        $data = $con->consultar($query);
        if ($con->getNumeroFilasConsultadas($data) > 0) {
            $row = $con->obnerFila($data);
        } else {
            die("Error: No se encontraron datos para el radicado.");
        }

        // Recuperar valores
        $numeroRadicado = $row['numeroRadicado'];
        $fechaRadicado = date('Y-m-d', strtotime($row['fechaRadicado']));
        $fechaRadicado = $row['fechaRadicado'];
        $remite = $row['remite'];
        $dependencia = $row['dependencia'];
        $serie = $row['serie'];
        $subSerie = $row['subSerie'];

        // Crear carpeta de destino
        $directory = realpath("../soportesRadicados/$numeroRadicado/Radicacion");
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        // Crear PDF
        $pdf = new TCPDF();
        $pdf->SetCreator('ERPSOFTSAS');
        $pdf->SetAuthor('ERPSOFTSAS');
        $pdf->SetTitle("Radicado $numeroRadicado");
        $pdf->SetMargins(20, 20, 20);
        $pdf->AddPage();

        // Contenido del PDF
        $html = "
        <h1 style='text-align: center;'>Certificado de Radicado</h1>
        <p><strong>Número de Radicado:</strong> $numeroRadicado</p>
        <p><strong>Fecha del Radicado:</strong> $fechaRadicado</p>
        <p><strong>Remite:</strong> $remite</p>
        <p><strong>Dependencia:</strong> $dependencia</p>
        <p><strong>Serie:</strong> $serie</p>
        <p><strong>Sub Serie:</strong> $subSerie</p>
        ";

        $pdf->writeHTML($html, true, false, true, false, '');
        $numeroRadicado  = "Numero_Radicado_".$numeroRadicado;
        // Guardar el archivo
        $pdfFilePath = "$directory/$numeroRadicado.pdf";
        $pdf->Output($pdfFilePath, 'F'); // Guardar en el servidor

        // Confirmación
        echo "El PDF ha sido generado correctamente y guardado en: $pdfFilePath";

        // Descarga del PDF en el quipo local.
        header ("Content-Disposition: attachment; filename=".$numeroRadicado.".pdf;" );
        header ("Content-Type: application/force-download");
        readfile($pdfFilePath);

        echo "<script languaje='javascript' type='text/javascript'>window.close();</script>";
    }
}

// Instanciar la clase y generar el PDF
$factura = new imprimirFactura();
$factura->codigo = $_GET["codigo"];
$factura->generarPDF();
