<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionUsuarios.php';

require_once('./tcpdf/tcpdf.php');

class RITPdf extends TCPDF {
    public function Header() {}
    public function Footer() {}
}

//$pdf = new RITPdf('P','mm','A4',true,'UTF-8',false);
$pdf = new RITPdf('P', 'mm', 'LEGAL', true, 'UTF-8', false);

$pdf->SetMargins(7,8,7);
$pdf->SetAutoPageBreak(false, 0);

$tipoDocumento = "NIT";
$numeroDocumento = "74323615";
$digitoVerificacion = "5";

$naturalezaJuridica = "Persona Natural";

$apellidosNombresRazon = "";
$codigoOrganizacion = "";
$direccionNotificacion = "";
$municipio = "PAIPA";
$departamento = "BOYACÁ";
$telefono = "3105555555";

$regimenTributario = "Simplificado";
$profesion = "";
$tarjetaProfesional = "";

$numeroMatricula = "15516";
$fechaMatriculaDia = "28";
$fechaMatriculaMes = "01";
$fechaMatriculaAno = "2013";
$estadoMatricula = "Matrícula";

$fechaInicioDia = "28";
$fechaInicioMes = "01";
$fechaInicioAno = "2013";

$nombreComercial = "INMOVILIARIA J J ROJAS";
$telefonoComercial = "3214444444";
$direccionActividad = "CR 21 24 32";
$codigoCatastral = "28";
$correoElectronico = "inmoviliariajjrojas@yahoo.com";


$pdf->AddPage();
$pdf->SetFont('helvetica','',9);

// ====== ENCABEZADO ======

// Marco completo
$pdf->Rect(7,8,202,341);

// Logo (espacio vacío por ahora)
$pdf->Rect(9,10,25,20);
$pdf->Image('tcpdf/pdf/img/logopazysalvo.png', 9, 10, 25, 20, '', '', '', false, 300, '', false, false, 0);


// Datos Municipio
$pdf->SetFont('helvetica','B',8);
$pdf->SetXY(50,11);
$pdf->Cell(120,5,'MUNICIPIO  DE PAIPA',0,0,'L');

$pdf->SetFont('helvetica','',7);
$pdf->SetXY(50,16);
$pdf->Cell(120,5,'891801240',0,0,'L');
$pdf->SetXY(50,21);
$pdf->Cell(120,5,'Carrera 22 No 25-14',0,0,'L');
$pdf->SetXY(50,26);
$pdf->Cell(120,5,'PAIPA - BOYACÁ',0,0,'L');

// ====== TÍTULO CENTRAL ======
$pdf->SetFont('helvetica','B',10);
$pdf->SetXY(0,38);
$pdf->Cell(210,6,'REGISTRO DE INFORMACIÓN TRIBUTARIA R.I.T',0,1,'C');

$pdf->SetFont('helvetica','B',10);
$pdf->SetXY(0,43);
$pdf->Cell(210,5,'FORMATO DE INSCRIPCION O NOVEDADES DEL ESTABLECIMIENTO',0,1,'C');

$pdf->SetXY(0,48);
$pdf->Cell(210,5,'IMPUESTO DE INDUSTRIA, COMERCIO, AVISOS Y TABLEROS',0,1,'C');

$pdf->SetFont('helvetica','B',10);
$pdf->SetXY(0,53);
$pdf->Cell(210,5,'SECRETARIA DE HACIENDA',0,1,'C');

// ====== SECCIÓN A ======
$pdf->SetFillColor(0,0,0);
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('helvetica','B',9);
$pdf->SetXY(7,65);
$pdf->Cell(196,6,'A. OPCION DE USO (Sólo puede marcar una casilla por formulario)',1,1,'L',true);

$pdf->SetTextColor(0,0,0);
$pdf->SetFont('helvetica','',9);

$pdf->SetXY(7,71);
$pdf->Cell(65,8,'1. Inscripción',0,0,'L');
$pdf->Rect(60,72,5,5); // casilla
$pdf->SetXY(60, 72.5);
$pdf->Cell(5,5,'X',0,0,'C');

$pdf->SetXY(80,71);
$pdf->Cell(65,8,'2. Actualización',0,0,'L');
$pdf->Rect(135,72,5,5);

$pdf->SetXY(150,71);
$pdf->Cell(65,8,'3. Cese de Actividades',0,0,'L');
$pdf->Rect(192,72,5,5);

// ====== SECCIÓN B ======
$y = 82;

// Encabezado sección
$pdf->SetFillColor(0,0,0);
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('helvetica','B',9);
$pdf->SetXY(7,$y);
$pdf->Cell(196,7,'B. DATOS DEL CONTRIBUYENTE',1,1,'L',true);

$pdf->SetTextColor(0,0,0);
$pdf->SetFont('helvetica','',8);
$y += 7;

// --- 4. Tipo de Documento / 5. Naturaleza Jurídica ---
$pdf->SetXY(7,$y);
$pdf->Cell(98,7,'4. Tipo de Documento',1,0,'L');
$pdf->Cell(98,7,'5. Naturaleza Jurídica',1,1,'L');
$y+=7;

// Tipo documento (cuadros + etiquetas)
$pdf->SetXY(7,$y);
$pdf->Cell(15,7,'CC',1,0,'L'); $pdf->Cell(6,7,'',1,0,'C');
$pdf->Cell(15,7,'NIT',1,0,'L'); $pdf->Cell(6,7,'X',1,0,'C'); 
$pdf->Cell(15,7,'TI',1,0,'L');$pdf->Cell(6,7,'',1,0,'C');
$pdf->Cell(25,7,'No.   ',1,0,'L');
$pdf->Cell(10,7,'DV ',1,1,'L');

// Naturaleza jurídica
$pdf->SetXY(105,$y);
$pdf->Cell(6,7,'',1,0,'C'); $pdf->Cell(25,7,'Persona Natural',1,0,'L');
$pdf->Cell(6,7,'X',1,0,'C'); $pdf->Cell(25,7,'Persona Jurídica',1,0,'L');
$pdf->Cell(6,7,'',1,0,'C'); $pdf->Cell(30,7,'Sociedad De Hecho',1,1,'L'); 
$y+=7;

// --- 6. Apellidos / Razón Social ---
$pdf->SetXY(7,$y);
$pdf->Cell(196,7,'6. Apellidos y Nombres ó Razón Social del Contribuyente',1,1,'L'); 
$y+=7;
$pdf->SetXY(7,$y);
$pdf->Cell(196,7,'SISTEMA ERPSOFT SOCIEDAD POR ACCIONES SIMPLIFICADA',1,1,'L');
$y+=7;

// --- 7. Código tipo organización ---
$pdf->SetXY(7,$y);
$pdf->Cell(98,7,'7. Código tipo organización (Ver instrucciones)',1,0,'L');
$pdf->Cell(98,7,'',1,1,'L');
$y+=7;

// --- 8. Dirección / 9. Municipio / 10. Departamento ---
$pdf->SetXY(7,$y);
$pdf->Cell(196,7,'8. Dirección de Notificación: CL 413-24 ',1,1,'L');
$y+=7;

$pdf->SetXY(7,$y);
$pdf->Cell(65,7,'9. Municipio: Duitama',1,0,'L');
$pdf->Cell(65,7,'10. Departamento: Boyacá',1,0,'L');
$pdf->Cell(66,7,'11. Teléfono (*): 3103063035',1,1,'L');
$y+=7;

// --- 11. Teléfono / 12. Régimen Tributario ---
$pdf->SetXY(7,$y);
$pdf->Cell(49,7,'12. Régimen Tributario',1,0,'L');
$pdf->Cell(6,7,'X',1,0,'C'); 
$pdf->Cell(63,7,'Simplificado',1,0,'L');
$pdf->Cell(6,7,'',1,0,'C');
$pdf->Cell(72,7,'Régimen Común',1,1,'L');
$pdf->SetXY(160,$y);
$y+=7;
// --- 13. Profesión / 14. Tarjeta Profesional ---
$pdf->SetXY(7,$y);
$pdf->Cell(49,7,'Profesional Independiente',1,0,'L');
$pdf->Cell(6,7,'',1,0,'C'); 
$pdf->Cell(69,7,'Tarjeta Profesional N°',1,0,'L');
$pdf->Cell(72,7,'Profesion',1,1,'L');
$y+=7;

// --- 13 Contador / 14 Revisor Fiscal ---
$pdf->SetXY(7,$y);
$pdf->Cell(98,7,'13. Contador',1,0,'L');
$pdf->Cell(98,7,'14. Revisor Fiscal',1,1,'L');
$y+=7;

// Sin márgenes internos
$pdf->setCellPaddings(0,0,0,0);
$lineHeight = 4.5; // alto de cada renglón (ajustable)

// Primera columna
$pdf->SetXY(7,$y);
$pdf->MultiCell(98,$lineHeight,"Nombre\nCédula\nTarjeta Profesional N°",1,'L',false,0);
// Segunda columna
$pdf->SetXY(105,$y);
$pdf->MultiCell(98,$lineHeight,"Nombre\nCédula\nTarjeta Profesional N°",1,'L',false,1);
// Avanzar Y exactamente la suma de las líneas
$y += ($lineHeight * 3);


// --- 15 Matrícula ---
$pdf->SetXY(7,$y);
$pdf->Cell(98,7,'15. Número de matrícula mercantil del contribuyente.',1,0,'L');
$pdf->Cell(98,7,'109556',1,1,'L');
$y+=7;


// --- 16 Fecha Matrícula / 17 Estado Registro ---
$pdf->SetXY(7,$y);
$pdf->Cell(49,7,'16. Fecha de la matrícula mercantil.',1,0,'L');
$pdf->Cell(49,7,'22-08-2022',1,0,'C'); 
$pdf->Cell(49,7,'17. Estado del registro mercantil',1,0,'L');
$pdf->Cell(6,7,'X',1,0,'C'); 
$pdf->Cell(18,7,'Matricula',1,0,'L');
$pdf->Cell(6,7,'M',1,0,'C');
$pdf->Cell(19,7,'Renovación',1,1,'L');
$pdf->SetXY(160,$y);
$y+=7;



// --- 15 Matrícula / 16 Fecha Matrícula / 17 Estado Registro ---
$pdf->SetXY(7,$y);
$pdf->Cell(49,7,'19. Fecha de inicio de Actividades',1,0,'L');
$pdf->Cell(29,7,'30-08-2023',1,0,'C');  
$pdf->Cell(79,7,'20. Nombre Comercial del Lugar en donde ejerce la actividad',1,0,'L');
$pdf->Cell(39,7,'SISTEMA ERPSOFT',1,0,'C');
$y+=7;


// --- 15 Matrícula / 16 Fecha Matrícula / 17 Estado Registro ---
$pdf->SetXY(7,$y);
$pdf->Cell(49,7,'21. Teléfono',1,0,'L');
$pdf->Cell(29,7,'3103063035',1,0,'C');  
$pdf->Cell(79,7,'22. Dirección del Lugar en donde ejerce la actividad',1,0,'L');
$pdf->Cell(39,7,'CALLE 6A NO 8-23',1,0,'C');
$y+=7;

// --- 15 Matrícula / 16 Fecha Matrícula / 17 Estado Registro ---
$pdf->SetXY(7,$y);
$pdf->Cell(49,7,'23. Codigo Catastral',1,0,'L');
$pdf->Cell(29,7,'',1,0,'C');  
$pdf->Cell(79,7,'24. Correo electrónico (*)',1,0,'L');
$pdf->Cell(39,7,'erpsoftsas@gmail.com',1,0,'C');
$y+=7;

// ====== SECCIÓN C ======
$pdf->SetFillColor(0,0,0);
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('helvetica','B',9);
$pdf->SetXY(7,$y);
$pdf->Cell(196,7,'C. REPRESENTACIÓN LEGAL',1,1,'L',true);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('helvetica','',8);
$y += 7;

$pdf->Cell(49,7,'25. Apellidos y Nombres',1,0,'L');
$pdf->Cell(79,7,'JAVIER RICARDO VILLATE MERCHAN',1,0,'C');  
$pdf->Cell(29,7,'26. Identificación',1,0,'L');
$pdf->Cell(39,7,'91507689',1,0,'C');
$y+=7;

$pdf->SetXY(7,$y);
$pdf->Cell(49,7,'27. Correo Electrónico',1,0,'L');
$pdf->Cell(147,7,'',1,0,'C');  
$y+=7;


// ====== SECCIÓN D ======
$pdf->SetFillColor(0,0,0);
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('helvetica','B',9);
$pdf->SetXY(7,$y);
$pdf->Cell(196,7,'D. CESE DE ACTIVIDADES',1,1,'L',true);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('helvetica','',8);
$y += 7;



// --- 11. Teléfono / 12. Régimen Tributario ---
$pdf->SetXY(7,$y);
$pdf->Cell(20,7,'28.Causal',1,0,'L');
$pdf->Cell(6,7,'',1,0,'C'); 
$pdf->Cell(23,7,' Fusión',1,0,'L');
$pdf->Cell(6,7,'',1,0,'C');
$pdf->Cell(40,7,' Escisión',1,0,'L');

$pdf->Cell(20,7,' Liquidación',1,0,'L');
$pdf->Cell(6,7,'',1,0,'C');
$pdf->Cell(20,7,' Otro?',1,0,'L');
$pdf->Cell(6,7,'',1,0,'C');
$pdf->Cell(15,7,'Cual? ',1,1,'C');
$pdf->SetXY(160,$y);
$y+=7;

// --- 11. Teléfono / 12. Régimen Tributario ---
$pdf->SetXY(7,$y);
$pdf->Cell(49,7,'27. Fecha de cese de actividades',1,0,'L');
$pdf->Cell(30,7,'',1,0,'C'); 
$pdf->Cell(63,7,' 29. Número de Establecimientos que clausura',1,0,'L');
$pdf->Cell(54,7,'',1,1,'C');
$pdf->SetXY(160,$y);
$y+=7;


// ====== SECCIÓN E ======
$pdf->SetFillColor(0,0,0);
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('helvetica','B',9);
$pdf->SetXY(7,$y);
$pdf->Cell(196,7,'E. FIRMAS',1,1,'L',true);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('helvetica','',8);
$y += 7;


// --- 30 Contribuyente o Representante Legal / 31 Persona que realiza el trámite ---
$pdf->SetXY(7,$y);
$pdf->Cell(98,7,'30. Contribuyente o Representante Legal',1,0,'L');
$pdf->Cell(98,7,'31. Persona que realiza el trámite',1,1,'L');
$y+=7;

// Sin márgenes internos
$pdf->setCellPaddings(0,0,0,0);
$lineHeight = 4.5; // alto de cada renglón (ajustable)

// Primera columna
$pdf->SetXY(7,$y);
$pdf->MultiCell(98,$lineHeight,"\n \n \n \n",1,'L',false,0);
// Segunda columna
$pdf->SetXY(105,$y);
$pdf->MultiCell(98,$lineHeight,"\n \n  \n \n",1,'L',false,1);
// Avanzar Y exactamente la suma de las líneas
$y += ($lineHeight * 3);


$pdf->SetXY(7,$y);
$pdf->Cell(98,7,'NOMBRE JAVIER RICARDO VILLATE MERCHAN',1,0,'L');
$pdf->Cell(98,7,'NOMBRE',1,1,'L');
$y+=7;

// Tipo documento (cuadros + etiquetas)
$pdf->SetXY(7,$y);
$pdf->Cell(15,7,'CC',1,0,'L'); $pdf->Cell(6,7,'X',1,0,'C');
$pdf->Cell(15,7,'CE',1,0,'L'); $pdf->Cell(6,7,'',1,0,'C'); 
$pdf->Cell(15,7,'OTRO',1,0,'L');$pdf->Cell(6,7,'',1,0,'C');
$pdf->Cell(35,7,'No.   91507689',1,1,'L');

// Naturaleza jurídica
$pdf->SetXY(105,$y);
$pdf->Cell(15,7,'CC',1,0,'L'); $pdf->Cell(6,7,'',1,0,'C');
$pdf->Cell(15,7,'CE',1,0,'L'); $pdf->Cell(6,7,'',1,0,'C'); 
$pdf->Cell(15,7,'OTRO',1,0,'L');$pdf->Cell(6,7,'',1,0,'C');
$pdf->Cell(35,7,'No.   ',1,1,'L');
$y+=7;




// ====== NOTA FINAL ======
$pdf->SetFont('helvetica','B',9);
$pdf->SetXY(7, $y);
$pdf->Cell(196,6,'PRESENTE ESTE FORMULARIO  EN ORIGINAL Y COPIA  CON CEDULA O NIT RESPECTIVO',1,1,'C');

$pdf->SetFont('helvetica','',9);
$pdf->SetXY(7, $y+6);
$pdf->MultiCell(196,6,'La inscripción o cierre al rit está establecida en el estatuto tributario así como las sanciones por no realizarlo oportunamente conforme las normas vigentes.',1,'C',false,1);

$y = $pdf->GetY();



// ====== SALIDA ======
$pdf->Output('RIT_Parte1.pdf','I');



/*

<td width="20%">17. Estado del Registro Mercantil:</td>
<td width="8%">Matricula</td>
<td width="3%"></td>
<td width="8%">Renovación</td>
<td width="3%"></td>


*/